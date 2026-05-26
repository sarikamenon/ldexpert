<?php

declare(strict_types=1);

use App\Domain\Schedule\Sub\Services\ScheduleSubRequestService;
use App\Models\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Support\CreatesSubCoverageFixtures;

uses(RefreshDatabase::class, CreatesSubCoverageFixtures::class);

beforeEach(function () {
    Mail::fake();
    config(['scheduling.sub_request_cutoff_hours' => 2]);
});

/**
 * Build the valid PUT payload the schedule edit form submits, derived from an
 * existing schedule so only the field under test changes.
 *
 * @return array<string, mixed>
 */
function scheduleUpdatePayload(Schedule $schedule): array
{
    return [
        'service_id' => $schedule->service_id,
        'ssa_id' => $schedule->ssa_id,
        'student_ids' => [$schedule->student_id],
        'schedule_date' => $schedule->schedule_date->toDateString(),
        'start_time' => '09:00',
        'duration_minutes' => 60,
        'location_details' => 'Updated by covering sub',
    ];
}

it('lets the covering sub update the schedule they are covering', function () {
    $w = $this->buildSubCoverageWorld();
    $request = app(ScheduleSubRequestService::class)
        ->create($w['A'], $w['schedule'], [$w['B']->id], null);
    app(ScheduleSubRequestService::class)->accept($w['B'], $request->fresh());

    $w['schedule']->refresh();
    expect($w['schedule']->sub_therapist_id)->toBe($w['B']->id);

    $this->actingAs($w['B'])
        ->putJson(route('therapist.schedule.update', $w['schedule']->id), scheduleUpdatePayload($w['schedule']))
        ->assertOk();
});

it('still lets the owner update their own schedule', function () {
    $w = $this->buildSubCoverageWorld();

    $this->actingAs($w['A'])
        ->putJson(route('therapist.schedule.update', $w['schedule']->id), scheduleUpdatePayload($w['schedule']))
        ->assertOk();
});

it('rejects a covering sub who tries to change the SSA on the covered schedule', function () {
    $w = $this->buildSubCoverageWorld();
    $request = app(ScheduleSubRequestService::class)
        ->create($w['A'], $w['schedule'], [$w['B']->id], null);
    app(ScheduleSubRequestService::class)->accept($w['B'], $request->fresh());

    $payload = scheduleUpdatePayload($w['schedule']->fresh());
    $payload['ssa_id'] = $payload['ssa_id'] + 999; // a different SSA the sub doesn't own

    $this->actingAs($w['B'])
        ->putJson(route('therapist.schedule.update', $w['schedule']->id), $payload)
        ->assertStatus(422)
        ->assertJsonValidationErrors('ssa_id');
});

it('rejects a covering sub who tries to change the student on the covered schedule', function () {
    $w = $this->buildSubCoverageWorld();
    $request = app(ScheduleSubRequestService::class)
        ->create($w['A'], $w['schedule'], [$w['B']->id], null);
    app(ScheduleSubRequestService::class)->accept($w['B'], $request->fresh());

    $payload = scheduleUpdatePayload($w['schedule']->fresh());
    $payload['student_ids'] = [$w['student']->id + 999]; // a different student

    $this->actingAs($w['B'])
        ->putJson(route('therapist.schedule.update', $w['schedule']->id), $payload)
        ->assertStatus(422)
        ->assertJsonValidationErrors('student_ids');
});

it('hides the schedule (404) from a therapist who is neither owner nor covering sub', function () {
    $w = $this->buildSubCoverageWorld();

    // Drop the ownership-gated fields so validation passes and the request reaches
    // the controller, where findForTherapist returns null for C → 404 (schedule hidden).
    $payload = scheduleUpdatePayload($w['schedule']);
    unset($payload['ssa_id'], $payload['student_ids']);

    // C is eligible but was never assigned to cover this schedule.
    $this->actingAs($w['C'])
        ->putJson(route('therapist.schedule.update', $w['schedule']->id), $payload)
        ->assertStatus(404);
});
