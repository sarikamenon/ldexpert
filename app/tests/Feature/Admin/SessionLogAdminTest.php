<?php

declare(strict_types=1);

use App\Models\SessionLog;
use App\Models\User;

it('renders the session logs index for admins', function () {
    $admin = User::factory()->admin()->create([
        'email' => 'admin@example.com',
    ]);

    $response = $this->actingAs($admin)
        ->get(route('admin.session-logs.index'));

    $response->assertOk();
    $response->assertSee('Session Logs');
    $response->assertSee('Amounts');
    $response->assertSee('Notes');
    $response->assertSee('Status');
});

it('applies the sent_back status filter from the query string', function () {
    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)
        ->get(route('admin.session-logs.index', ['status' => 'sent_back']));

    $response->assertOk();
    $response->assertViewHas('filters', static function (array $filters): bool {
        return ($filters['status'] ?? null) === 'sent_back';
    });
});

it('filters the data endpoint by sent_back status', function () {
    $admin = User::factory()->admin()->create();

    SessionLog::factory()->sentBack()->create();
    SessionLog::factory()->submitted()->create();

    $response = $this->actingAs($admin)->postJson(route('admin.session-logs.data'), [
        '_token' => csrf_token(),
        'draw' => 1,
        'start' => 0,
        'length' => 25,
        'search' => ['value' => '', 'regex' => 'false'],
        'filter_school_id' => '',
        'filter_student_id' => '',
        'filter_therapist_id' => '',
        'filter_service_id' => '',
        'filter_ssa_id' => '',
        'filter_status' => 'sent_back',
        'filter_date_from' => '',
        'filter_date_to' => '',
    ]);

    $response->assertOk();
    expect($response->json('recordsTotal'))->toBe(1)
        ->and($response->json('recordsFiltered'))->toBe(1)
        ->and($response->json('data'))->toHaveCount(1);
});

it('renders session dates in the viewing admin timezone, not the row owner timezone', function () {
    $admin = User::factory()->admin()->create(['timezone' => 'America/New_York']);

    // The row owner (therapist) stays on the factory default (UTC). A 03:00
    // UTC session falls on the previous calendar day for the New York viewer,
    // so the date cell proves the viewer's timezone is used.
    SessionLog::factory()->create([
        'session_date' => '2025-06-10',
        'start_time' => '2025-06-10 03:00:00',
        'end_time' => '2025-06-10 03:30:00',
    ]);

    $response = $this->actingAs($admin)->postJson(route('admin.session-logs.data'), [
        '_token' => csrf_token(),
        'draw' => 1,
        'start' => 0,
        'length' => 25,
        'search' => ['value' => '', 'regex' => 'false'],
    ]);

    $response->assertOk();
    expect($response->json('data.0.0'))->toContain('Jun 09, 2025');
});
