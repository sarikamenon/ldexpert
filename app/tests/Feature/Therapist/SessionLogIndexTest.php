<?php

declare(strict_types=1);

use App\Enums\SessionLogStatus;
use App\Models\SessionLog;
use App\Models\User;
use Carbon\Carbon;

afterEach(function () {
    Carbon::setTestNow();
});

it('shows the merged status columns and actions to therapists', function () {
    Carbon::setTestNow('2025-01-15 10:00:00');

    $therapist = User::factory()->therapist()->create();

    SessionLog::factory()->draft()->create([
        'therapist_id' => $therapist->id,
        'status' => SessionLogStatus::DRAFT,
        'session_date' => Carbon::now()->startOfMonth(),
    ]);

    $response = $this->actingAs($therapist)
        ->get(route('therapist.session-logs.index'));

    $response->assertOk();
    $response->assertViewIs('therapist.session-logs.index');
    $response->assertSee('Amounts');
    $response->assertSee('Notes');
    $response->assertSee('Status');
});

it('defaults the index to the current month for therapists', function () {
    Carbon::setTestNow('2025-01-15 10:00:00');

    $therapist = User::factory()->therapist()->create();

    SessionLog::factory()->create([
        'therapist_id' => $therapist->id,
        'session_date' => Carbon::now()->copy()->startOfMonth(),
    ]);

    $response = $this->actingAs($therapist)
        ->get(route('therapist.session-logs.index'));

    $response->assertOk();
    $response->assertViewIs('therapist.session-logs.index');
    $response->assertViewHas('datatableUrl');
});
